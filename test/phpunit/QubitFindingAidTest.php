<?php

/**
 * @covers \QubitFindingAid
 *
 * @internal
 */
class QubitFindingAidTest extends \PHPUnit\Framework\TestCase
{
    public function testSetResourceFromConstructor()
    {
        $resource = new QubitInformationObject();
        $findingAid = new QubitFindingAid($resource);

        $this->assertSame($resource, $findingAid->getResource());
    }

    public function testSetResource()
    {
        $resource1 = new QubitInformationObject();
        $resource2 = new QubitInformationObject();
        $resource2->id = '11111';

        $findingAid = new QubitFindingAid($resource1);

        $findingAid->setResource($resource2);

        $this->assertSame($resource2, $findingAid->getResource());
    }

    public function testSetResourceTypeError()
    {
        $findingAid = new QubitFindingAid(new QubitInformationObject());

        $this->expectException(TypeError::class);
        $findingAid->setResource('foo');
    }

    public function testSetLogger()
    {
        $logger = new sfNoLogger(new sfEventDispatcher());
        $findingAid = new QubitFindingAid(new QubitInformationObject());

        $findingAid->setLogger($logger);

        $this->assertSame($logger, $findingAid->getLogger());
    }

    public function testSetLoggerFromConstructorOption()
    {
        $logger = new sfNoLogger(new sfEventDispatcher());
        $findingAid = new QubitFindingAid(
      new QubitInformationObject(),
      ['logger' => $logger]
    );

        $this->assertSame($logger, $findingAid->getLogger());
    }

    public function testSetPath()
    {
        $path = '/path/to/foo.pdf';
        $findingAid = new QubitFindingAid(new QubitInformationObject());
        $findingAid->setPath($path);

        $this->assertSame($path, $findingAid->getPath());
    }
}
