<script setup>
import Alert from '@/components/Alert.vue'
import Image from '@/components/Image.vue'
import Paragraph from '@/components/Paragraph.vue'
import SecondaryTopic from '@/components/SecondaryTopic.vue'
import TertiaryTopic from '@/components/TertiaryTopic.vue'
import Breadcrumbs from '@/components/lists/Breadcrumbs.vue'
import ConceptList from '@/components/lists/ConceptList.vue'

const cloneRepo = [
  { id: 'Version control', value: 'Git', children: [] },
  { id: 'URL', value: 'https://github.com/Eschaton31/ohasis-dqt', children: [] },
  {
    id: 'Directory',
    value: 'Choose a directory you have read and write permissions to. This is where the project will be stored.',
    children: [],
  },
]
</script>

<template>
  <SecondaryTopic class="new-page" start="3" title="Version Control">
    <Paragraph>
      Version control records every change to the codebase, creating a complete, auditable history and a single source
      of truth. This enables team members to experiment safely, collaborate efficiently, and roll back to a known‑good
      state when necessary.
    </Paragraph>
    <Paragraph>
      The pipeline uses Git as its distributed version control system (VCS), with the codebase maintained in a GitHub
      repository:
      <a class="text-link" href="https://github.com/Eschaton31/ohasis-dqt"> https://github.com/Eschaton31/ohasis-dqt</a
      >.
    </Paragraph>
    <Paragraph>
      Data managers are added as collaborators to this repository so they can contribute changes directly through
      commits, pull requests, and code reviews. This ensures that updates to pipeline logic, configuration, and
      documentation follow the same controlled and transparent workflow as all other development activities.
    </Paragraph>
    <Alert title="Note">
      It is recommended that data managers create their own feature branches from the
      <codetext>main</codetext>
      branch. This allows them to develop and maintain their own changelists without altering the official project
      history. Once the work is ready and has undergone adequate review, changes can be merged back into
      <codetext>main</codetext>
      through a pull request, ensuring that all updates are vetted, traceable, and consistent with the team’s standards.
    </Alert>
    <Paragraph>
      For ease of use, the instructions in this section reference the PyCharm VCS interface, which provides a
      straightforward, visual workflow for common Git operations. For more advanced or specialized tasks, users may
      consult online Git resources or use Git Bash directly to access the full range of Git commands.
    </Paragraph>
    <TertiaryTopic title="Cloning the repo">
      <Paragraph>
        Ensure that PyCharm is installed and that you have a GitHub account with access to the repository.
      </Paragraph>
      <Paragraph>
        Open PyCharm, under the <Breadcrumbs :list="['Menu', 'File', 'Project from Version Control...']" />. In the
        <b>Clone Repository</b> dialog choose <b>Repository URL</b>:
      </Paragraph>
      <Image
        description="PyCharm's Clone Repository dialog box"
        height="h-80"
        src="/img/screenshots/pycharm-clone-repo.png" />
      <ConceptList :list="cloneRepo" />
      <Paragraph>
        After clicking <b>Clone</b>, PyCharm will download the files from the repository and open the project once
        cloning is complete.
      </Paragraph>
    </TertiaryTopic>
    <TertiaryTopic title="Updating the Project (Tracked Branch)">
      <Paragraph>
        Once changes have been committed and pushed to your tracked branch (by you or collaborators), you’ll need to
        pull the approved updates back into your local environment. This ensures your local branch stays aligned with
        the latest reviewed and merged changes.
      </Paragraph>
      <Paragraph>
        Open the <b>Update Project</b> dialog found in <Breadcrumbs :list="['Menu', 'Git', 'Update Project...']" />.
        Choose <b>Merge incoming changes into the current branch</b> then press <b>OK</b>. This will pull any commits
        that have not been replicated locally and apply those changes to the affected files.
      </Paragraph>
      <Image
        description="PyCharm's Update Project dialog box"
        height="h-36"
        src="/img/screenshots/pycharm-update-project.png" />
    </TertiaryTopic>
    <TertiaryTopic title="Make Changes and Commit">
      <Paragraph>
        When you edit scripts locally, Git records new files, file modifications, and line‑level edits. After you’ve
        reviewed and cleaned your work (run tests, remove debug code, and tidy formatting), stage and commit the changes
        to your branch so they become part of the branch history and are ready to be pushed for review.
      </Paragraph>
      <Paragraph>
        Open the <b>Commit Panel</b> via <Breadcrumbs :list="['Menu', 'Git', 'Commit...']" />. The panel lists files
        changed since your last commit and shows their status by color: blue = modified, green = new and staged, red =
        untracked or not included in the changelist.
      </Paragraph>
      <Paragraph>
        Before committing, make sure that you've properly tested the code changes and formatted accordingly;; stage only
        the files that belong to the same logical change; and remove debug code and temporary prints.
      </Paragraph>
      <Paragraph>
        When committing changes, a commit message is necessary, indicated by the field in the lower part of the panel.
        Keep the subject short and descriptive. However, when more context is needed explain why the change was made,
        not just what changed.
      </Paragraph>
      <Image description="PyCharm's Commit panel" height="h-80" src="/img/screenshots/pycharm-commit-panel.png" />
    </TertiaryTopic>
    <TertiaryTopic title="Push Your Commits">
      <Paragraph>
        When you finish a feature or accumulate multiple commits, push them to the remote so collaborators on the same
        branch receive your updates and the branch history is recorded centrally.
      </Paragraph>
      <Paragraph>
        To do so, open the <b>Push Dialog</b> via <Breadcrumbs :list="['Menu', 'Git', 'Push...']" />. The dialog box
        will provide a list of commits you've yet to push into the remote repository. Clicking <b>Push</b> will finalize
        the process and upload your changes and record it into the official version history of the branch.
      </Paragraph>
      <Image description="PyCharm's Push dialog box" height="h-80" src="/img/screenshots/pycharm-push.png" />
    </TertiaryTopic>
  </SecondaryTopic>
</template>
