# mybb-build
The MyBB package build script. **Requires [Docker](https://www.docker.com/).**

A Docker image [is built](https://github.com/mybb/mybb-build/blob/master/Dockerfile) with tools needed to run the [Phing](https://www.phing.info/) build [script](https://github.com/mybb/mybb-build/blob/master/build.xml).

#### Directory Structure
- `input/source/` - directory containing development source files (e.g. a git repository),
- `input/previous-source/` - directory containing source files of the previous release,
- `input/patches/` - directory containing git patch/diff files to be applied,
- `input/package-addendum/` - additional files attached to the release package,
- `input/build.properties` - variables specific to the target release.

## Building Packages

**It is recommended to run the tools with at least 2048 MB of memory available to PHP and external tools.**

1. Clone the repository.
2. Prepare `input/` files according to the Directory Structure above.

   The build source can be also fetched from the `mybb/mybb` repository automatically if a branch (or tag) is specified in `input/build.properties`.

2. Build the Docker image:
```
$ docker-compose build
```

3. Run the built `phing` service and execute:
   - the `dist-set` task to build the release package only:

    ```
    $ docker-compose run phing dist-set
    ```

   - the `full` task to build the release and update packages:

    ```
    $ docker-compose run phing full
    ```

## Build Environment

The built `phing` service, with volumes `sources/` (read-only) and `output/` mounted to `/home/user/`, passes subsequent `docker-compose run` arguments to `phing` (the PHP build script).

During Phing execution the files operated on are located in the `build/` directory, and once all tasks are completed are copied to `output/` (outside of the Docker container).

#### VirtualBox Shared Folders on Windows hosts
On Windows-based hosts it may be necessary to add the directory in the Docker Machine's **Settings → Shared Folders** (e.g. `d:\mybb-build` named `d/mybb-build`) and manually mount the VirtualBox Shared Folders filesystem (`vboxsf`) to the `default` machine:
```
$ docker-machine ssh default 'sudo mount -t vboxsf d/mybb-build //d/mybb-build'
$ docker-machine restart
```
