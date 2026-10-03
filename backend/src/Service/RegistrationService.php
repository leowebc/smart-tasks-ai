<?php

namespace App\Service;

use App\Dto\RegisterUserData;
use App\Entity\User;
use App\Exception\UsernameAlreadyTakenException;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function register(RegisterUserData $data): User
    {
        $username = trim($data->username);
        if ($username === '' || $data->password === '') {
            throw new \InvalidArgumentException('Usuário e senha são obrigatórios.');
        }
        if (strlen($username) > 50) {
            throw new \InvalidArgumentException('Usuário deve ter no máximo 50 caracteres.');
        }
        if ($this->users->findOneByUsername($username) instanceof User) {
            throw new UsernameAlreadyTakenException('Username já cadastrado.');
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->hasher->hashPassword($user, $data->password));

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException) {
            throw new UsernameAlreadyTakenException('Username já cadastrado.');
        }

        return $user;
    }
}
