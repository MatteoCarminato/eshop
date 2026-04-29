<div class="col-xl-6">
    <div class="card">
        <div class="card-header align-items-center d-flex">
            <h4 class="card-title mb-0 flex-grow-1">Striped Rows</h4>
            <div class="flex-shrink-0">
                <div class="form-check form-switch form-switch-right form-switch-md">
                    <label for="striped-rows-showcode" class="form-label text-muted">Show Code</label>
                    <input class="form-check-input code-switcher" type="checkbox" id="striped-rows-showcode">
                </div>
            </div>
        </div><!-- end card header -->

        <div class="card-body">
            <p class="text-muted">Use <code>table-striped</code> class to add zebra-striping to any table row within the
                &lt;tbody&gt;.</p>
            <div class="live-preview">
                <div class="table-responsive">
                    <table class="table table-striped table-nowrap align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Customer</th>
                                <th scope="col">Date</th>
                                <th scope="col">Invoice</th>
                                <th scope="col">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-medium">01</td>
                                <td>Bobby Davis</td>
                                <td>Nov 14, 2021</td>
                                <td>$2,410</td>
                                <td><span class="badge bg-success">Confirmed</span></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">02</td>
                                <td>Christopher Neal</td>
                                <td>Nov 21, 2021</td>
                                <td>$1,450</td>
                                <td><span class="badge bg-warning">Waiting</span></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">03</td>
                                <td>Monkey Karry</td>
                                <td>Nov 24, 2021</td>
                                <td>$3,500</td>
                                <td><span class="badge bg-success">Confirmed</span></td>
                            </tr>
                            <tr>
                                <td class="fw-medium">04</td>
                                <td>Aaron James</td>
                                <td>Nov 25, 2021</td>
                                <td>$6,875</td>
                                <td><span class="badge bg-danger">Cancelled</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="d-none code-view">
                <pre class="language-markup" style="height: 275px;"><code>&lt;!-- Striped Rows --&gt;
&lt;table class=&quot;table table-striped&quot;&gt;
    &lt;thead&gt;
        &lt;tr&gt;
            &lt;th scope=&quot;col&quot;&gt;Id&lt;/th&gt;
            &lt;th scope=&quot;col&quot;&gt;Customer&lt;/th&gt;
            &lt;th scope=&quot;col&quot;&gt;Date&lt;/th&gt;
            &lt;th scope=&quot;col&quot;&gt;Invoice&lt;/th&gt;
            &lt;th scope=&quot;col&quot;&gt;Action&lt;/th&gt;
        &lt;/tr&gt;
    &lt;/thead&gt;
    &lt;tbody&gt;
        &lt;tr&gt;
            &lt;th scope=&quot;row&quot;&gt;1&lt;/th&gt;
            &lt;td&gt;Bobby Davis&lt;/td&gt;
            &lt;td&gt;Nov 14, 2021&lt;/td&gt;
            &lt;td&gt;$2,410&lt;/td&gt;
            &lt;td&gt;&lt;span class=&quot;badge bg-success&quot;&gt;Confirmed&lt;/span&gt;&lt;/td&gt;
        &lt;/tr&gt;
        &lt;tr&gt;
            &lt;th scope=&quot;row&quot;&gt;2&lt;/th&gt;
            &lt;td&gt;Christopher Neal&lt;/td&gt;
            &lt;td&gt;Nov 21, 2021&lt;/td&gt;
            &lt;td&gt;$1,450&lt;/td&gt;
            &lt;td&gt;&lt;span class=&quot;badge bg-warning&quot;&gt;Waiting&lt;/span&gt;&lt;/td&gt;
        &lt;/tr&gt;
        &lt;tr&gt;
            &lt;th scope=&quot;row&quot;&gt;3&lt;/th&gt;
            &lt;td&gt;Monkey Karry&lt;/td&gt;
            &lt;td&gt;Nov 24, 2021&lt;/td&gt;
            &lt;td&gt;$3,500&lt;/td&gt;
            &lt;td&gt;&lt;span class=&quot;badge bg-success&quot;&gt;Confirmed&lt;/span&gt;&lt;/td&gt;
        &lt;/tr&gt;
        &lt;tr&gt;
            &lt;th scope=&quot;row&quot;&gt;4&lt;/th&gt;
            &lt;td&gt;Aaron James&lt;/td&gt;
            &lt;td&gt;Nov 25, 2021&lt;/td&gt;
            &lt;td&gt;$6,875&lt;/td&gt;
            &lt;td&gt;&lt;span class=&quot;badge bg-danger&quot;&gt;Cancelled&lt;/span&gt;&lt;/td&gt;
        &lt;/tr&gt;
    &lt;/tbody&gt;
&lt;/table&gt;</code></pre>
            </div>
        </div><!-- end card-body -->
    </div><!-- end card -->
</div>
<!-- end col -->