// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'subscription_invoice.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

SubscriptionInvoice _$SubscriptionInvoiceFromJson(Map<String, dynamic> json) =>
    SubscriptionInvoice(
      baseFee: json['base_fee'] as String,
      uuid: json['uuid'] as String,
      number: json['number'] as String,
      storeId: (json['store_id'] as num).toInt(),
      storeSubscriptionId: (json['store_subscription_id'] as num?)?.toInt(),
      tariffPlanId: (json['tariff_plan_id'] as num?)?.toInt(),
      period: json['period'] as String,
      periodStart: DateTime.parse(json['period_start'] as String),
      periodEnd: DateTime.parse(json['period_end'] as String),
      terminalsCount: (json['terminals_count'] as num).toInt(),
      includedTerminals: (json['included_terminals'] as num).toInt(),
      id: (json['id'] as num).toInt(),
      feePerTerminal: json['fee_per_terminal'] as String,
      lines: (json['lines'] as List<dynamic>)
          .map((e) => e as Map<String, dynamic>)
          .toList(),
      amount: json['amount'] as String,
      status: SubscriptionInvoiceStatus.fromJson(json['status'] as String),
      dueDate: DateTime.parse(json['due_date'] as String),
      issuedAt: DateTime.parse(json['issued_at'] as String),
      paidAt: json['paid_at'] == null
          ? null
          : DateTime.parse(json['paid_at'] as String),
      voidedAt: json['voided_at'] == null
          ? null
          : DateTime.parse(json['voided_at'] as String),
      voidedBy: json['voided_by'],
      voidReason: json['void_reason'] as String?,
      ledgerTransactionId: (json['ledger_transaction_id'] as num?)?.toInt(),
      voidRequestReason: json['void_request_reason'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      kind: InvoiceKind.fromJson(json['kind'] as String),
      sequence: (json['sequence'] as num).toInt(),
      paidAmount: json['paid_amount'] as String,
      voidRequestedBy: (json['void_requested_by'] as num?)?.toInt(),
      voidRequestedAt: json['void_requested_at'] == null
          ? null
          : DateTime.parse(json['void_requested_at'] as String),
      voidTransactionId: (json['void_transaction_id'] as num?)?.toInt(),
      voidRequester: json['void_requester'] == null
          ? null
          : User.fromJson(json['void_requester'] as Map<String, dynamic>),
      store: json['store'] == null
          ? null
          : Store.fromJson(json['store'] as Map<String, dynamic>),
      subscription: json['subscription'] == null
          ? null
          : StoreSubscription.fromJson(
              json['subscription'] as Map<String, dynamic>,
            ),
      plan: json['plan'] == null
          ? null
          : TariffPlan.fromJson(json['plan'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$SubscriptionInvoiceToJson(
  SubscriptionInvoice instance,
) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'number': instance.number,
  'store_id': instance.storeId,
  'store_subscription_id': instance.storeSubscriptionId,
  'tariff_plan_id': instance.tariffPlanId,
  'period': instance.period,
  'period_start': instance.periodStart.toIso8601String(),
  'period_end': instance.periodEnd.toIso8601String(),
  'terminals_count': instance.terminalsCount,
  'included_terminals': instance.includedTerminals,
  'base_fee': instance.baseFee,
  'fee_per_terminal': instance.feePerTerminal,
  'lines': instance.lines,
  'amount': instance.amount,
  'status': instance.status,
  'due_date': instance.dueDate.toIso8601String(),
  'issued_at': instance.issuedAt.toIso8601String(),
  'paid_at': instance.paidAt?.toIso8601String(),
  'voided_at': instance.voidedAt?.toIso8601String(),
  'voided_by': instance.voidedBy,
  'void_reason': instance.voidReason,
  'ledger_transaction_id': instance.ledgerTransactionId,
  'void_transaction_id': instance.voidTransactionId,
  'created_at': instance.createdAt?.toIso8601String(),
  'updated_at': instance.updatedAt?.toIso8601String(),
  'kind': instance.kind,
  'sequence': instance.sequence,
  'paid_amount': instance.paidAmount,
  'void_requested_by': instance.voidRequestedBy,
  'void_requested_at': instance.voidRequestedAt?.toIso8601String(),
  'void_request_reason': instance.voidRequestReason,
  'void_requester': instance.voidRequester,
  'store': instance.store,
  'subscription': instance.subscription,
  'plan': instance.plan,
};
