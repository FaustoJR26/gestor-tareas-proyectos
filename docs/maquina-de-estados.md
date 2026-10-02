# Máquina de estados: Tarea

Los estados y las transiciones están declarados en un solo lugar: `src/estados_tarea.php`.

## Estados

| Estado | Significado | Terminal |
|---|---|---|
| pendiente | La tarea fue creada y nadie ha empezado | No |
| en_progreso | Se está trabajando en ella | No |
| en_revision | El trabajo terminó y espera aprobación | No |
| completada | Aprobada y cerrada | Sí |

## Transiciones permitidas

| Desde | Hacia | Quién la ejecuta | Condición |
|---|---|---|---|
| pendiente | en_progreso | Estándar o Administrador | Ninguna |
| en_progreso | en_revision | Estándar o Administrador | Ninguna |
| en_revision | completada | Administrador | La revisión se aprueba |
| en_revision | en_progreso | Administrador | La revisión se rechaza |

## Transiciones prohibidas

| Desde | Hacia | Motivo |
|---|---|---|
| pendiente | completada | No se puede completar sin trabajarla ni revisarla |
| completada | en_progreso | Una tarea completada no se reabre |

## Estado terminal

`completada`: de ella no sale ninguna transición.