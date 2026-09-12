---
paths:
  - 'app/**'
---

# App

## Las fechas son inmutables: nunca uses $date->addX() por su efecto
AppServiceProvider llama `Date::use(CarbonImmutable::class)`, así que `now()` y los casts de fecha devuelven CarbonImmutable.

`$cursor->addWeek()` NO muta `$cursor`: devuelve una instancia nueva. Un bucle tipo `for ($c = $inicio; $c->lte($fin); $c->addWeek())` se cuelga para siempre (sin error, solo CPU al 100%).

Siempre reasigna: `$cursor = $cursor->addWeek();`. Mejor aún, usa un bucle contado cuando sepas cuántas iteraciones son.

## Illuminate\Support\Carbon sí muta: usa copy() antes de endOfMonth/addWeek
`Date::use(CarbonImmutable::class)` solo cambia lo que devuelven `now()` y los casts de fecha. `Illuminate\Support\Carbon::create(...)` sigue siendo la clase MUTABLE, y sus métodos devuelven `$this`.

Por eso `$end = $start->endOfMonth()` deja `$start` y `$end` apuntando al mismo objeto, y un `while ($cursor->lte($end)) { $cursor = $cursor->addWeek(); }` se cuelga para siempre (sin error, CPU al 100%).

Siempre `$start->copy()->endOfMonth()`, `$cursor->copy()->addWeek()`. Y en bucles de fechas poné un tope contado (`for ($i = 1; $i <= 6 && ...; $i++)`) para que un descuido no cuelgue la petición.
