<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Class Clients
 * @package Modules\Clients\Controllers
 * @property CI_DB_query_builder $db
 * @property CI_Loader $load
 * @property Layout $layout
 * @property Mdl_Clients $mdl_clients
 * @property Mdl_Client_Custom $mdl_client_custom
 * @property Mdl_Custom_Fields $mdl_custom_fields
 * @property Mdl_Invoices $mdl_invoices
 * @property Mdl_Notes $mdl_notes
 * @property Mdl_Payments $mdl_payments
 * @property Mdl_Quotes $mdl_quotes
 */
class Clients extends User_Controller
{
    /**
     * Clients constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('mdl_clients');
    }

    /**
     * Index page, redirects to 'clients/status/active'
     */
    public function index()
    {
        // Display active clients by default
        redirect('clients/status/active');
    }

    /**
     * Returns all clients based on the given status and page no.
     * Example: 'clients/status/active' returns all active clients
     * @param string $status
     * @param int $page
     */
    public function status($status = 'active', $page = 0)
    {
        if (is_numeric(array_search($status, array('active', 'inactive')))) {
            $function = 'is_' . $status;
            $this->mdl_clients->$function();
        }

        $this->mdl_clients->with_total_balance()->paginate(site_url('clients/status/' . $status), $page);
        $clients = $this->mdl_clients->result();

        $this->layout->set(
            array(
                'clients' => $clients,
                'filter_display' => true,
                'filter_placeholder' => lang('filter_clients'),
                'filter_method' => 'filter_clients'
            )
        );

        $this->layout->buffer('content', 'clients/index');
        $this->layout->render();
    }

    /**
     * Returns the form
     * If an ID was provided the form will be filled with the data of the client
     * for the given ID and can be used as an edit form.
     * @param null $id
     */
    public function form($id = null)
    {
        if ($this->input->post('btn_cancel')) {
            redirect('clients');
        }

        // Set validation rule based on is_update
        if ($this->input->post('is_update') == 0 && $this->input->post('client_name') != '') {
            $check = $this->db->get_where('ip_clients',
                array('client_name' => $this->input->post('client_name')))->result();
            if (!empty($check)) {
                set_alert('danger', lang('client_already_exists'));
                redirect('clients/form');
            }
        }

        if ($this->mdl_clients->run_validation()) {
            $id = $this->mdl_clients->save($id);

            $this->load->model('custom_fields/mdl_client_custom');

            $this->mdl_client_custom->save_custom($id, $this->input->post('custom'));

            redirect('clients/view/' . $id);
        }

        if ($id and !$this->input->post('btn_submit')) {
            if (!$this->mdl_clients->prep_form($id)) {
                show_404();
            }

            $this->load->model('custom_fields/mdl_client_custom');
            $this->mdl_clients->set_form_value('is_update', true);

            $client_custom = $this->mdl_client_custom->where('client_id', $id)->get();

            if ($client_custom->num_rows()) {
                $client_custom = $client_custom->row();

                unset($client_custom->client_id, $client_custom->client_custom_id);

                foreach ($client_custom as $key => $val) {
                    $this->mdl_clients->set_form_value('custom[' . $key . ']', $val);
                }
            }
        } elseif ($this->input->post('btn_submit')) {
            if ($this->input->post('custom')) {
                foreach ($this->input->post('custom') as $key => $val) {
                    $this->mdl_clients->set_form_value('custom[' . $key . ']', $val);
                }
            }
        }

        $this->load->model('custom_fields/mdl_custom_fields');
        $this->load->helper('country');

        $this->layout->set('custom_fields', $this->mdl_custom_fields->by_table('custom_client')->get()->result());
        $this->layout->set('countries', get_country_list(lang('cldr')));
        $this->layout->set('selected_country', $this->mdl_clients->form_value('country') ?:
            $this->mdl_settings->setting('default_country'));

        $this->layout->buffer('content', 'clients/form');
        $this->layout->render();
    }

    /**
     * Returns the details page for a client for the given ID
     * @param $client_id
     */
    public function view($client_id)
    {
        $this->load->helper('country');

        $this->load->model('notes/mdl_notes');
        $this->load->model('invoices/mdl_invoices');
        $this->load->model('quotes/mdl_quotes');
        $this->load->model('payments/mdl_payments');
        $this->load->model('custom_fields/mdl_custom_fields');

        $client = $this->mdl_clients->with_total()->with_total_balance()->with_total_paid()->where('clients.id',
            $client_id)->get()->row();

        if (!$client) {
            show_404();
        }

        $this->layout->set(
            array(
                'client' => $client,
                'notes' => $this->mdl_notes->get_notes('client', $client_id),
                'invoices' => $this->mdl_invoices->by_client($client_id)->limit(20)->get()->result(),
                'quotes' => $this->mdl_quotes->by_client($client_id)->limit(20)->get()->result(),
                'payments' => $this->mdl_payments->by_client($client_id)->limit(20)->get()->result(),
                'custom_fields' => $this->mdl_custom_fields->by_table('custom_client')->get()->result(),
                'quote_statuses' => $this->mdl_quotes->statuses(),
                'invoice_statuses' => $this->mdl_invoices->statuses(),
            )
        );

        $this->layout->buffer(
            array(
                array(
                    'invoice_table',
                    'invoices/partial_invoice_table'
                ),
                array(
                    'quote_table',
                    'quotes/partial_quote_table'
                ),
                array(
                    'payment_table',
                    'payments/partial_payment_table'
                ),
                array(
                    'content',
                    'clients/view'
                )
            )
        );

        $this->layout->render();
    }

    /**
     * Deletes a client from the database based on the given ID
     * @param $client_id
     */
    public function delete($client_id)
    {
        $this->mdl_clients->delete($client_id);
        redirect('clients');
    }

}
