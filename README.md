# mybb-prep
Maintenance repository for security fixes and meta updates.

# Phing builds
[Phing](https://www.phing.info/) is used to generate release packages with associated data from the development repository.

### Requirements
- [PHP](https://secure.php.net/) (https://secure.php.net/manual/en/install.php)
- [PEAR](https://pear2.php.net/) (https://pear.php.net/manual/en/installation.getting.php)
- [Archive_Tar PEAR package](https://pear.php.net/package/Archive_Tar)
- [Phing](https://www.phing.info/) (http://www.phing.info/get/phing-latest.phar)

### Working directory structure
- `dist-addendum/` - additional files attached to the release package,
- `patch/` - directory containing git patch/diff files to be applied,
- `previous-clean-source/` - directory containing source files of the previous release,
- `raw-source/` - directory containing development source files (e.g. a git repository),
- `build.xml` - project-specific Phing package building instructions,
- `build.properties` - variables specific to the target release,

After Phing is run the output files are located in the `build/` directory.

### Package building
You can execute Phing with `php phing-latest.phar`.
Add  `-buildfile path/build.xml` to specify custom path to the working directory.
You can display Phing help with `-help` and list the build task names and descriptions with `-list`. Add `-quiet` to only display warnings and errors. Add the task name to execute it.

Take a look into `build.xml` to get familiar wit the build process and task (`<target>`) order.

Before running the tasks remove PHP's memory limit, or increase it to ~1G.
