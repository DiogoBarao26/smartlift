# SmartLift — Sistema Inteligente de Controle de Elevadores

Aplicação web que simula um elevador de um prédio. As pessoas usam cartões de identificação para embarcar, e o sistema valida o cartão, o peso e a lotação antes de autorizar a viagem. O deslocamento do elevador é animado na interface e todas as operações importantes ficam gravadas em SQLite em memória.

## Tecnologias

- PHP 8.3+ com Laravel 13
- API REST em JSON
- SQLite em memória (`:memory:`), com Eloquent, migrations e transações
- HTML5, CSS3, JavaScript e Bootstrap 5
- Fetch API para a comunicação com o backend
- PHPUnit para as regras de capacidade, cartão, embarque, desembarque e estatísticas

O PHP 8.0 do XAMPP não executa esta versão do Laravel. O projeto foi preparado para o PHP 8.4.

## Estrutura principal

```text
app/
  Enums/                         Status, direção e tipos de evento
  Exceptions/ElevatorException.php
  Http/Controllers/Api/          Pessoas, cartões, elevador e estatísticas
  Http/Controllers/Auth/         Login do painel administrativo
  Http/Middleware/EnsureAdmin.php
  Http/Requests/                 Validação das entradas
  Http/Resources/                Respostas JSON de pessoas e eventos
  Models/                        People, elevators, trips, passageiros, chamadas e eventos
  Services/ElevatorService.php   Regras do elevador e algoritmo de atendimento
  Services/StatisticsService.php Indicadores calculados no banco
database/migrations/             Tabelas e chaves estrangeiras
database/seeders/                Administrador, elevador e cartões de exemplo
public/css/smartlift.css         Interface e animação das portas
public/js/smartlift.js           Painel do elevador
public/js/admin.js               Painel administrativo
resources/views/                 Telas do elevador, login e administração
routes/api.php                   Endpoints REST
routes/web.php                   Telas e autenticação
tests/Feature/                   Testes de capacidade, movimento e estatísticas
```

## Requisitos no Windows

- PHP 8.3 ou superior, com as extensões `openssl`, `pdo_sqlite`, `sqlite3`, `mbstring`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `curl` e `zip`
- Composer
- Navegador atual

Nesta máquina o PHP 8.4 ficou em `%LOCALAPPDATA%\php-8.4\php.exe`. O `iniciar.bat` usa esse caminho quando ele existe.

## Banco SQLite em memória

Não é preciso instalar MySQL nem criar um banco. A conexão fica no `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

O SQLite em memória existe só enquanto a conexão da requisição está aberta. Por isso, ao final de cada requisição a aplicação grava uma cópia em `storage/framework/smartlift-memory.sqlite` e, na próxima, carrega essa cópia de volta para a memória. Apague esse arquivo para voltar aos dados de exemplo. Os testes automatizados usam outro `:memory:`, isolado, sem essa cópia.

## Instalação

No PowerShell, dentro da pasta do projeto:

```powershell
$php = "$env:LOCALAPPDATA\php-8.4\php.exe"
& $php "$env:TEMP\composer.phar" install
Copy-Item .env.example .env
& $php artisan key:generate
```

Na primeira abertura da interface, as migrations e o seeder rodam sozinhos dentro do SQLite em memória e carregam:

- elevador no andar 1, com 5 andares, 300 kg e 4 passageiros
- sete cartões de exemplo
- administrador `admin@smartlift.local` / `admin123`

## Executar

Dê dois cliques em `iniciar.bat` ou rode:

```powershell
& "$env:LOCALAPPDATA\php-8.4\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

Abra [http://127.0.0.1:8000](http://127.0.0.1:8000).

## Como usar a simulação

1. O elevador aparece no andar atual, com as portas fechadas.
2. Escolha o andar e clique em **Chamar elevador**. O carro se desloca pelo poço e abre as portas.
3. Digite um código, por exemplo `ANA-1001`, ou escolha uma pessoa em **Aguardando embarque** e clique em **Inserir cartão**.
4. Se houver lugar e peso disponível, a pessoa entra e o destino do cartão entra na rota.
5. As portas permanecem abertas por alguns segundos para outro embarque. Depois fecham e o elevador parte.
6. No andar autorizado, o passageiro desembarca. O botão **Desembarcar** faz o mesmo para quem já está no destino.
7. **Estatísticas** lê os indicadores gravados no banco.
8. **Painel administrativo** exige login e permite cadastrar cartões, bloquear cartões, mudar andares e capacidades e consultar o histórico.

Cartões de exemplo:

| Código | Pessoa | Andar | Peso | Status |
| --- | --- | --- | --- | --- |
| ANA-1001 | Ana Beatriz Nogueira | 5 | 62 kg | Ativo |
| BRU-1002 | Bruno Carvalho | 3 | 84 kg | Ativo |
| CAR-1003 | Carla Mendes | 4 | 70 kg | Ativo |
| DIE-1004 | Diego Albuquerque | 2 | 96 kg | Ativo |
| ELI-1005 | Elisa Pacheco | 5 | 58 kg | Ativo |
| FAB-1006 | Fábio Monteiro | 4 | 190 kg | Ativo |
| HEL-1007 | Helena Duarte | 3 | 73 kg | Bloqueado |

Fábio, com 190 kg, serve para testar o limite de 300 kg junto de outros passageiros. Helena demonstra a recusa de cartão bloqueado.

## Regras de negócio

- O elevador atende primeiro os pedidos na direção atual e só depois inverte o sentido.
- Cada passageiro soma o próprio peso. O embarque é recusado se passar do peso ou da quantidade máxima.
- A mensagem de recusa vem do backend, por exemplo: `Embarque não autorizado: capacidade máxima de peso excedida`.
- Cartão inexistente, bloqueado ou com destino fora do prédio não autoriza o embarque.
- A mesma pessoa não pode estar duas vezes a bordo.
- O desembarque só ocorre no andar autorizado do cartão, com as portas abertas.
- As portas fecham antes do movimento.
- O elevador não inicia viagem se a carga já estiver acima do limite.
- Embarque, desembarque, chamada e movimento usam transação.
- O peso do embarque fica em `trip_passengers.weight_at_entry` e não muda se o cadastro da pessoa for alterado depois.
- Subidas e descidas contam apenas deslocamentos concluídos (`trips.status = completed`). A viagem do passageiro (`boarding` / `fulfilled`) não entra de novo nessa conta.
- Cada embarque efetivo conta como uma pessoa transportada, mesmo que a pessoa use o elevador outra vez.
- O peso total transportado é a soma dos pesos de cada embarque, não a carga simultânea da cabine.

## Testes

```powershell
& "$env:LOCALAPPDATA\php-8.4\php.exe" artisan test
```

Os testes usam SQLite em memória e cobrem capacidade de peso e de passageiros, cartão inválido ou bloqueado, embarque duplicado, desembarque, deslocamento, atendimento por direção e estatísticas persistidas.

## API

As respostas de sucesso seguem este formato:

```json
{
  "success": true,
  "message": "Estado atual do elevador.",
  "data": {}
}
```

Erros de validação ou de regra de negócio:

```json
{
  "success": false,
  "message": "Embarque não autorizado: capacidade máxima de peso excedida."
}
```

### Consultar o elevador

`GET /api/elevator`

```json
{
  "success": true,
  "message": "Estado atual do elevador.",
  "data": {
    "elevator": {
      "name": "Elevador Social",
      "current_floor": 1,
      "direction": "idle",
      "direction_label": "Parado",
      "status": "idle",
      "status_label": "Parado",
      "max_weight_kg": 300,
      "max_passengers": 4,
      "door_open": false
    },
    "passengers": [],
    "passenger_count": 0,
    "current_weight_kg": 0,
    "pending_calls": []
  }
}
```

### Chamar o elevador

`POST /api/elevator/call`

```json
{
  "requested_floor": 3,
  "requested_direction": "up"
}
```

A resposta traz o estado final e um roteiro de animação (`close_doors`, `move`, `open_doors`) para a interface sincronizar o movimento.

### Embarcar

`POST /api/elevator/board`

```json
{ "card_code": "ANA-1001" }
```

Resposta quando autorizado:

```json
{
  "success": true,
  "message": "Embarque autorizado para Ana Beatriz Nogueira.",
  "data": {
    "passenger_count": 1,
    "current_weight_kg": 62,
    "should_depart": true
  }
}
```

### Desembarcar

`POST /api/elevator/alight`

```json
{ "person_id": 1 }
```

Sem `person_id`, desembarcam todos os passageiros cujo destino é o andar atual.

### Partir para o próximo atendimento

`POST /api/elevator/depart`

### Pessoas e cartões

- `GET /api/people`
- `GET /api/cards/ANA-1001`
- `POST /api/people` — requer login
- `PATCH /api/people/{id}` — requer login
- `PATCH /api/people/{id}/status` — requer login, corpo `{ "card_status": "blocked" }`

Cadastro:

```json
{
  "name": "João Silva",
  "card_code": "JOA-4001",
  "destination_floor": 2,
  "weight_kg": 75
}
```

### Configuração

`PATCH /api/elevator/settings` — requer login

```json
{
  "name": "Elevador Social",
  "min_floor": 1,
  "max_floor": 5,
  "max_weight_kg": 300,
  "max_passengers": 4
}
```

### Histórico e estatísticas

- `GET /api/elevator/history`
- `GET /api/statistics?from=2026-10-01&to=2026-10-16`
- `GET /api/statistics/daily?date=2026-10-16`
- `GET /api/statistics/passengers`
- `GET /api/statistics/weight`

O painel administrativo usa a sessão do login. As rotas de cadastro e configuração passam pelo middleware `admin`.
