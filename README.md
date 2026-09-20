# Gen-gen

A Joomla component that models generators.

A generator turns a model into files. Writing one by hand means writing control
flow: a loop over the model, a condition, a template name, an output path, a set
of variables — the same five things over and over, in PHP, where the mapping is
something you reconstruct by reading rather than something you can see.

Gen-gen models that mapping instead. A generator here is a list of rules, and one
rule reads as a sentence:

> **for** each node this *source pattern* yields, **when** it passes these
> *conditions*, render this *template* to this *output path*, **binding** these
> variables.

Everything a rule may say is a choice offered from the target's own published
vocabulary. You do not type the name of a selector; you pick one the target
registered. A rule naming something that does not exist cannot be saved.

## Where it sits

| Repo | Contains |
|---|---|
| [`generator-core`](https://github.com/HermanPeeren/generator-core) | the engine, `Yepr\Gen\Core` — including `Rule`, which executes what is modelled here |
| [`Exten-gen`](https://github.com/HermanPeeren/Exten-gen) | models CMS extensions and generates them; publishes the `joomla6` target |
| **`Gen-gen`** | models generators |
| `Meta-gen` | models the model language, and generates the forms models are filled in with |

Gen-gen depends on none of the components it models generators for. A target
publishes what its rules may say as a `*.vocabulary.json` inside its own
component, and Gen-gen finds it. Install Exten-gen and `joomla6` appears in the
target list.

## Status

Step 2.3 of the [rework plan](https://github.com/HermanPeeren/Exten-gen/blob/main/docs/rework-plan.md).
Gen-gen generates a generator, and the generated one produces the same bytes as
the hand-written one it replaces:

```bash
composer generate     # a rule file and the classes that carry it
composer acceptance   # run it over Exten-gen's golden models and compare
```

> 228 files compared, all identical to the approved output.

Not yet here, and deliberately: the component's MVC, its manifest, its package
and its release workflow. That is step 2.4 — the forms exist and are checked,
but there is no screen to open them on yet.

## Developing

See [docs/development.md](docs/development.md).

```bash
composer install && composer test
```
