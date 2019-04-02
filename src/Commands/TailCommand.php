<?php

namespace Chriha\CliLog\Commands;

use Chriha\CliLog\Tail;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TailCommand extends Command
{

    /** @var array */
    protected $reds = [ 'alert', 'emergency', 'error', 'fatal', 'critical', 'failed' ];

    /** @var array */
    protected $yellows = [ 'warning', 'debug' ];

    /** @var array */
    protected $greens = [ 'info', 'processed' ];


    public function configure()
    {
        $this->setName( 'tail' )
            ->setDescription( 'Tail a file.' )
            ->addArgument( 'file', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'The file you want to tail.' );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    public function execute( InputInterface $input, OutputInterface $output ) : void
    {
        $styleRed  = new OutputFormatterStyle( 'red' );
        $styleBlue = new OutputFormatterStyle( 'blue' );

        $output->getFormatter()->setStyle( 'error', $styleRed );
        $output->getFormatter()->setStyle( 'default', $styleBlue );

        $tail = new Tail( __DIR__ . '/../../save.json' );

        foreach ( $input->getArgument( 'file' ) as $file )
        {
            $tail->addFile( $file );
            $output->writeln( "Listening to <comment>{$file}</comment> ..." );
        }

        $tail->listen( function( $file, $chunk ) use ( $output )
        {
            foreach ( explode( "\n", $chunk ) as $line )
            {
                if ( substr( $line, 0, 1 ) !== '[' ) continue;

                $line = trim( $line );

                if ( empty( $line ) ) continue;

                preg_match( "/^(\[[0-9-\s:]+\]) ([a-z]+)[\s\.]?([a-zA-Z]+): (.+)/", $line, $parts );

                if ( empty( $parts ) || count( $parts ) < 5 ) continue;

                if ( in_array( strtolower( $parts[3] ), $this->reds ) )
                {
                    $parts[3] = "<error>{$parts[3]}</error>";
                    //$parts[4] = "<error>{$parts[4]}</error>";
                }
                elseif ( in_array( strtolower( $parts[3] ), $this->yellows ) )
                {
                    $parts[3] = "<comment>{$parts[3]}</comment>";
                    //$parts[4] = "<comment>{$parts[4]}</comment>";
                }
                elseif ( in_array( strtolower( $parts[3] ), $this->greens ) )
                {
                    $parts[3] = "<info>{$parts[3]}</info>";
                }

                $text = "<default>{$parts[1]}</default> {$parts[2]} {$parts[3]} {$parts[4]}";

                $output->writeln( $text );

                $system = trim( shell_exec( "uname" ) );
                $string = str_replace( '\'', '', explode( ' {', $parts[4] )[0] );
                // $string = explode( ' {', $parts[4] )[0];

                if ( $system === "Darwin" )
                {
                    $message = strip_tags( $parts[3] );
                    shell_exec( `osascript -e 'display notification "{$string}" with title "{$message}"'` );
                }
            }
        } );
    }

}