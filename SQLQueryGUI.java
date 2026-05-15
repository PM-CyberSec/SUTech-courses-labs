import javax.swing.*;
import javax.swing.border.*;
import java.awt.*;
import java.awt.event.*;
import java.sql.*;
import java.util.Vector;

public class SQLQueryGUI extends JFrame {
    private JTextField hostField;
    private JTextField portField;
    private JTextField databaseField;
    private JTextField usernameField;
    private JPasswordField passwordField;
    private JButton connectButton;
    private JLabel statusLabel;
    private JTextArea queryArea;
    private JTextArea resultsArea;
    private Connection connection;

    public SQLQueryGUI() {
        setTitle("MySQL Query Executor");
        setSize(800, 600);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setLocationRelativeTo(null);

        // Create main panel with padding
        JPanel mainPanel = new JPanel();
        mainPanel.setLayout(new BoxLayout(mainPanel, BoxLayout.Y_AXIS));
        mainPanel.setBorder(BorderFactory.createEmptyBorder(10, 10, 10, 10));

        // Create connection panel
        createConnectionPanel(mainPanel);

        // Create query panel
        createQueryPanel(mainPanel);

        // Create results panel
        createResultsPanel(mainPanel);

        add(mainPanel);
    }

    private void createConnectionPanel(JPanel mainPanel) {
        JPanel connectionPanel = new JPanel();
        connectionPanel.setBorder(BorderFactory.createTitledBorder("Database Connection"));
        connectionPanel.setLayout(new GridBagLayout());
        GridBagConstraints gbc = new GridBagConstraints();
        gbc.insets = new Insets(5, 5, 5, 5);

        // Host
        gbc.gridx = 0; gbc.gridy = 0;
        connectionPanel.add(new JLabel("Host:"), gbc);
        gbc.gridx = 1;
        hostField = new JTextField("localhost", 20);
        connectionPanel.add(hostField, gbc);

        // Port
        gbc.gridx = 2;
        connectionPanel.add(new JLabel("Port:"), gbc);
        gbc.gridx = 3;
        portField = new JTextField("3306", 10);
        connectionPanel.add(portField, gbc);

        // Database
        gbc.gridx = 0; gbc.gridy = 1;
        connectionPanel.add(new JLabel("Database:"), gbc);
        gbc.gridx = 1;
        databaseField = new JTextField(20);
        connectionPanel.add(databaseField, gbc);

        // Username
        gbc.gridx = 2;
        connectionPanel.add(new JLabel("Username:"), gbc);
        gbc.gridx = 3;
        usernameField = new JTextField(20);
        connectionPanel.add(usernameField, gbc);

        // Password
        gbc.gridx = 0; gbc.gridy = 2;
        connectionPanel.add(new JLabel("Password:"), gbc);
        gbc.gridx = 1;
        passwordField = new JPasswordField(20);
        connectionPanel.add(passwordField, gbc);

        // Connect button
        gbc.gridx = 3;
        connectButton = new JButton("Connect");
        connectButton.addActionListener(e -> connectToDatabase());
        connectionPanel.add(connectButton, gbc);

        // Status label
        gbc.gridx = 0; gbc.gridy = 3;
        gbc.gridwidth = 4;
        statusLabel = new JLabel("Not connected");
        statusLabel.setForeground(Color.RED);
        connectionPanel.add(statusLabel, gbc);

        mainPanel.add(connectionPanel);
    }

    private void createQueryPanel(JPanel mainPanel) {
        JPanel queryPanel = new JPanel();
        queryPanel.setBorder(BorderFactory.createTitledBorder("SQL Query"));
        queryPanel.setLayout(new BorderLayout());

        queryArea = new JTextArea(5, 80);
        queryArea.setLineWrap(true);
        JScrollPane queryScrollPane = new JScrollPane(queryArea);
        queryPanel.add(queryScrollPane, BorderLayout.CENTER);

        JButton executeButton = new JButton("Execute Query");
        executeButton.addActionListener(e -> executeQuery());
        queryPanel.add(executeButton, BorderLayout.SOUTH);

        mainPanel.add(queryPanel);
    }

    private void createResultsPanel(JPanel mainPanel) {
        JPanel resultsPanel = new JPanel();
        resultsPanel.setBorder(BorderFactory.createTitledBorder("Query Results"));
        resultsPanel.setLayout(new BorderLayout());

        resultsArea = new JTextArea(10, 80);
        resultsArea.setEditable(false);
        JScrollPane resultsScrollPane = new JScrollPane(resultsArea);
        resultsPanel.add(resultsScrollPane, BorderLayout.CENTER);

        mainPanel.add(resultsPanel);
    }

    private void connectToDatabase() {
        try {
            if (connection != null) {
                connection.close();
            }

            String host = hostField.getText().trim();
            String port = portField.getText().trim();
            String database = databaseField.getText().trim();
            String username = usernameField.getText().trim();
            String password = new String(passwordField.getPassword());

            if (host.isEmpty() || port.isEmpty() || database.isEmpty() || username.isEmpty()) {
                JOptionPane.showMessageDialog(this, "Please fill in all connection fields", "Error", JOptionPane.ERROR_MESSAGE);
                return;
            }

            String url = "jdbc:mysql://" + host + ":" + port + "/" + database;
            connection = DriverManager.getConnection(url, username, password);

            if (connection != null) {
                DatabaseMetaData metaData = connection.getMetaData();
                statusLabel.setText("Connected to MySQL Server version " + metaData.getDatabaseProductVersion());
                statusLabel.setForeground(Color.GREEN);
                connectButton.setText("Disconnect");
                connectButton.removeActionListener(connectButton.getActionListeners()[0]);
                connectButton.addActionListener(e -> disconnectDatabase());
                JOptionPane.showMessageDialog(this, "Connected to database successfully!");
            }
        } catch (SQLException ex) {
            statusLabel.setText("Connection failed");
            statusLabel.setForeground(Color.RED);
            JOptionPane.showMessageDialog(this, "Failed to connect to database:\n" + ex.getMessage(),
                    "Error", JOptionPane.ERROR_MESSAGE);
            ex.printStackTrace();
        }
    }

    private void disconnectDatabase() {
        try {
            if (connection != null) {
                connection.close();
                connection = null;
                connectButton.setText("Connect");
                connectButton.removeActionListener(connectButton.getActionListeners()[0]);
                connectButton.addActionListener(e -> connectToDatabase());
                statusLabel.setText("Disconnected");
                statusLabel.setForeground(Color.RED);
                JOptionPane.showMessageDialog(this, "Disconnected from database");
            }
        } catch (SQLException ex) {
            ex.printStackTrace();
        }
    }

    private void executeQuery() {
        if (connection == null) {
            JOptionPane.showMessageDialog(this, "Please connect to a database first", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        String query = queryArea.getText().trim();
        if (query.isEmpty()) {
            JOptionPane.showMessageDialog(this, "Please enter a query", "Error", JOptionPane.ERROR_MESSAGE);
            return;
        }

        try {
            Statement stmt = connection.createStatement();
            resultsArea.setText("");

            if (query.toUpperCase().startsWith("SELECT")) {
                ResultSet rs = stmt.executeQuery(query);
                ResultSetMetaData metaData = rs.getMetaData();
                int columnCount = metaData.getColumnCount();

                // Display column names
                StringBuilder header = new StringBuilder("Columns: ");
                for (int i = 1; i <= columnCount; i++) {
                    header.append(metaData.getColumnName(i));
                    if (i < columnCount) header.append(", ");
                }
                resultsArea.append(header.toString() + "\n\n");

                // Display results
                while (rs.next()) {
                    StringBuilder row = new StringBuilder();
                    for (int i = 1; i <= columnCount; i++) {
                        row.append(rs.getString(i));
                        if (i < columnCount) row.append(", ");
                    }
                    resultsArea.append(row.toString() + "\n");
                }
            } else {
                int rowsAffected = stmt.executeUpdate(query);
                resultsArea.append("Query executed successfully. Rows affected: " + rowsAffected);
            }
        } catch (SQLException ex) {
            resultsArea.setText("ERROR: " + ex.getMessage());
            ex.printStackTrace();
        }
    }

    public static void main(String[] args) {
        SwingUtilities.invokeLater(() -> {
            try {
                UIManager.setLookAndFeel(UIManager.getSystemLookAndFeelClassName());
            } catch (Exception e) {
                e.printStackTrace();
            }
            new SQLQueryGUI().setVisible(true);
        });
    }
} 