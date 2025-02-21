<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    private $user;

    #[ORM\Column(type: 'datetime')]
    private $transaction_date;

    #[ORM\Column(type: 'float')]
    private $amount;

    #[ORM\Column(type: 'string', length: 255)]
    private $billing_address;

    #[ORM\Column(type: 'string', length: 255)]
    private $billing_city;

    #[ORM\Column(type: 'string', length: 10)]
    private $billing_postcode;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getTransactionDate(): ?\DateTimeInterface
    {
        return $this->transaction_date;
    }

    public function setTransactionDate(\DateTimeInterface $transaction_date): self
    {
        $this->transaction_date = $transaction_date;
        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billing_address;
    }

    public function setBillingAddress(string $billing_address): self
    {
        $this->billing_address = $billing_address;
        return $this;
    }

    public function getBillingCity(): ?string
    {
        return $this->billing_city;
    }

    public function setBillingCity(string $billing_city): self
    {
        $this->billing_city = $billing_city;
        return $this;
    }

    public function getBillingPostcode(): ?string
    {
        return $this->billing_postcode;
    }

    public function setBillingPostcode(string $billing_postcode): self
    {
        $this->billing_postcode = $billing_postcode;
        return $this;
    }
}
