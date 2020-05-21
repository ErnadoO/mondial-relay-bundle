<?php
namespace Ernadoo\MondialRelayBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class MondialRelayTwigExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var string
     */
	private $customerCode;

    public function __construct(string $customerCode)
    {
    	$this->customerCode = $customerCode;
    }
    /**
     * @inheritDoc
     */
    public function getGlobals(): array
    {
        return [
            'ernadoo_mondial_relay' => [
            	'customer_code' => $this->customerCode,
            ],
        ];
    }
}