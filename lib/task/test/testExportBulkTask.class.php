<?php

/**
 *
 */

/**
 * Bulk export data test
 *
 * Test that exporting an resource as and EAD XML document with the export:bulk
 * task creates a document that matches the reference document
 *
 * @package    symfony
 * @subpackage task
 * @author     David Juhasz <djjuhasz@gmail.com>
 */
class testExportBulkTask extends arBaseTask
{
  protected $namespace        = 'test';
  protected $name             = 'export-bulk';
  protected $briefDescription = 'Test export:bulk task output against a reference file';

  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_OPTIONAL, 'The application name', 'qubit'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'cli'),
      new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'propel'),
    ));
  }

  /**
   * @see sfTask
   */
  public function execute($arguments = array(), $options = array())
  {
    parent::execute($arguments, $options);

    $reference = './test/fixtures/clara-bernhardt-fonds.ead.xml';
    $test = $this->exportEad('clara-bernhardt-fonds');

    // Set document creation date deterministically
    foreach ([$reference, $test] as $file)
    {
      $this->makeEadDeterministic($file);
    }

    if ($this->assertSameFile($reference, $test))
    {
      $this->log($this->formatter->format('PASSED!', 'INFO'));
    }
    else
    {
      $this->log($this->formatter->format('FAILED!', 'ERROR'));
      $this->log(
        sprintf(
          "EAD files '%s' and '%s' are not the same",
          $reference, $test
        )
      );
    }
  }

  private function exportEad(string $slug): string
  {
    $filename = sprintf('%s.ead.xml', $slug);
    //$filename = tempnam(sys_get_temp_dir(), 'ead');

    $exporter = new exportBulkTask($this->dispatcher, $this->formatter);
    $exporter->run(['path' => $filename], ['single-slug' => $slug]);

    return $filename;
  }

  private function assertSameFile(string $a, string $b): bool
  {
    // Check if filesize is different
    if (filesize($a) !== filesize($b))
    {
      return false;
    }

    // Check if content is different
    $ah = fopen($a, 'rb');
    $bh = fopen($b, 'rb');

    $result = true;
    while(!feof($ah))
    {
      if(fread($ah, 8192) != fread($bh, 8192))
      {
        $result = false;
        break;
      }
    }

    fclose($ah);
    fclose($bh);

    return $result;
  }

  private function makeEadDeterministic(string $file)
  {
    $ead = simplexml_load_file($file);

    // Set creation date to "0" (Unix Epoch time)
    $date = $ead->eadheader->profiledesc->creation->date;
    $date->attributes()->normal = gmdate('o-m-d', 0);
    $date[0] = gmdate('o-m-d H:i e', 0);

    // Remove bioghist @id attribute
    unset($ead->archdesc->bioghist['id']);

    file_put_contents($file, $ead->asXML());
  }
}
