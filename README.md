# mybb-build
The MyBB package build script.

# Phing builds
[Phing](https://www.phing.info/) is used to generate release packages with associated data from the development repository.

### Requirements
- [PHP](https://secure.php.net/) (https://secure.php.net/manual/en/install.php)
- [Archive_Tar PEAR package](https://pear.php.net/package/Archive_Tar)
- [git](https://git-scm.com/)
- [Phing](https://www.phing.info/) (http://www.phing.info/get/phing-latest.phar)

### Working directory structure
- `sources/dist-addendum/` - additional files attached to the release package,
- `sources/patch/` - directory containing git patch/diff files to be applied,
- `sources/previous-clean-source/` - directory containing source files of the previous release,
- `sources/raw-source/` - directory containing development source files (e.g. a git repository),
- `sources/build.properties` - variables specific to the target release,
- `build.xml` - project-specific Phing package building instructions.

After Phing is run the build files are located in the `build/` directory. Output packages and metadata are copied to `output/`.

### Package building
You can execute Phing with `php phing-latest.phar`.
Add  `-buildfile path/build.xml` to specify custom path to the working directory.
You can display Phing help with `-help` and list the build task names and descriptions with `-list`. Add `-quiet` to only display warnings and errors. Add the task name to execute it.

Take a look into `build.xml` to get familiar wit the build process and task (`<target>`) order.

Before running the tasks remove PHP's memory limit, or increase it to ~1G.

# Docker
It is possible to run the build script with [Docker](https://www.docker.com/) and have it set up dependencies automatically.

Fetch the repository and place source files according to the description above and build the Docker service from the directory:
```
$ docker-compose build
```

Once built it will be possible to run commands:
```
$ docker-compose run phing-service phing -l
```

The above example will run the `phing-service` with volumes `sources/` (read-only) and `build/` mounted to `/home/user/` and the `phing -l` command inside, listing available Phing tasks.

#### VirtualBox Shared Folders on Windows hosts
On Windows-based hosts it may be necessary to add the directory in the Docker Machine's **Settings → Shared Folders** (e.g. `d:\mybb-build` named `d/mybb-build`) and manually mount the VirtualBox Shared Folders filesystem (`vboxsf`) to the `default` machine:
```
$ docker-machine ssh default 'sudo mount -t vboxsf d/mybb-build //d/mybb-build'
$ docker-machine restart
```

#### Docker Machine memory
It might be necessary to increase the available memory of the Docker Machine above 1 GB for internal `exec()` calls to run successfully.
