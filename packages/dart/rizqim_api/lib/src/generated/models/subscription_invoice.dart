// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'invoice_kind.dart';
import 'store.dart';
import 'store_subscription.dart';
import 'subscription_invoice_status.dart';
import 'tariff_plan.dart';
import 'user.dart';

part 'subscription_invoice.g.dart';

@JsonSerializable()
class SubscriptionInvoice {
  const SubscriptionInvoice({
    required this.baseFee,
    required this.uuid,
    required this.number,
    required this.storeId,
    required this.storeSubscriptionId,
    required this.tariffPlanId,
    required this.period,
    required this.periodStart,
    required this.periodEnd,
    required this.terminalsCount,
    required this.includedTerminals,
    required this.id,
    required this.feePerTerminal,
    required this.lines,
    required this.amount,
    required this.status,
    required this.dueDate,
    required this.issuedAt,
    required this.paidAt,
    required this.voidedAt,
    required this.voidedBy,
    required this.voidReason,
    required this.ledgerTransactionId,
    required this.voidRequestReason,
    required this.createdAt,
    required this.updatedAt,
    required this.kind,
    required this.sequence,
    required this.paidAmount,
    required this.voidRequestedBy,
    required this.voidRequestedAt,
    required this.voidTransactionId,
    this.voidRequester,
    this.store,
    this.subscription,
    this.plan,
  });

  factory SubscriptionInvoice.fromJson(Map<String, Object?> json) =>
      _$SubscriptionInvoiceFromJson(json);

  final int id;
  final String uuid;
  final String number;
  @JsonKey(name: 'store_id')
  final int storeId;
  @JsonKey(name: 'store_subscription_id')
  final int? storeSubscriptionId;
  @JsonKey(name: 'tariff_plan_id')
  final int? tariffPlanId;
  final String period;
  @JsonKey(name: 'period_start')
  final DateTime periodStart;
  @JsonKey(name: 'period_end')
  final DateTime periodEnd;
  @JsonKey(name: 'terminals_count')
  final int terminalsCount;
  @JsonKey(name: 'included_terminals')
  final int includedTerminals;
  @JsonKey(name: 'base_fee')
  final String baseFee;
  @JsonKey(name: 'fee_per_terminal')
  final String feePerTerminal;
  final List<Map<String, dynamic>> lines;
  final String amount;
  final SubscriptionInvoiceStatus status;
  @JsonKey(name: 'due_date')
  final DateTime dueDate;
  @JsonKey(name: 'issued_at')
  final DateTime issuedAt;
  @JsonKey(name: 'paid_at')
  final DateTime? paidAt;
  @JsonKey(name: 'voided_at')
  final DateTime? voidedAt;
  @JsonKey(name: 'voided_by')
  final dynamic voidedBy;
  @JsonKey(name: 'void_reason')
  final String? voidReason;
  @JsonKey(name: 'ledger_transaction_id')
  final int? ledgerTransactionId;
  @JsonKey(name: 'void_transaction_id')
  final int? voidTransactionId;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final InvoiceKind kind;
  final int sequence;
  @JsonKey(name: 'paid_amount')
  final String paidAmount;
  @JsonKey(name: 'void_requested_by')
  final int? voidRequestedBy;
  @JsonKey(name: 'void_requested_at')
  final DateTime? voidRequestedAt;
  @JsonKey(name: 'void_request_reason')
  final String? voidRequestReason;
  @JsonKey(name: 'void_requester')
  final User? voidRequester;
  final Store? store;
  final StoreSubscription? subscription;
  final TariffPlan? plan;

  Map<String, Object?> toJson() => _$SubscriptionInvoiceToJson(this);
}
