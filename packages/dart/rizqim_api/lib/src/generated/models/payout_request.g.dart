// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'payout_request.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

PayoutRequest _$PayoutRequestFromJson(Map<String, dynamic> json) =>
    PayoutRequest(
      id: (json['id'] as num).toInt(),
      storeId: (json['store_id'] as num).toInt(),
      amount: json['amount'] as String,
      cardNumber: json['card_number'] as String,
      status: json['status'] as String,
      processedBy: json['processed_by'],
      processedAt: json['processed_at'] == null
          ? null
          : DateTime.parse(json['processed_at'] as String),
      notes: json['notes'] as String?,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
      resolutionNotes: json['resolution_notes'] as String?,
      firstApprovedBy: (json['first_approved_by'] as num?)?.toInt(),
      firstApprovedAt: json['first_approved_at'] == null
          ? null
          : DateTime.parse(json['first_approved_at'] as String),
      firstApprovalNote: json['first_approval_note'] as String?,
      nettedAmount: json['netted_amount'] as String?,
      transferredAmount: json['transferred_amount'] as String?,
      netting: json['netting'] as List<dynamic>?,
      store: json['store'] == null
          ? null
          : Store.fromJson(json['store'] as Map<String, dynamic>),
      firstApprover: json['first_approver'] == null
          ? null
          : User.fromJson(json['first_approver'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$PayoutRequestToJson(PayoutRequest instance) =>
    <String, dynamic>{
      'id': instance.id,
      'store_id': instance.storeId,
      'amount': instance.amount,
      'card_number': instance.cardNumber,
      'status': instance.status,
      'processed_by': instance.processedBy,
      'processed_at': instance.processedAt?.toIso8601String(),
      'notes': instance.notes,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
      'resolution_notes': instance.resolutionNotes,
      'first_approved_by': instance.firstApprovedBy,
      'first_approved_at': instance.firstApprovedAt?.toIso8601String(),
      'first_approval_note': instance.firstApprovalNote,
      'netted_amount': instance.nettedAmount,
      'transferred_amount': instance.transferredAmount,
      'netting': instance.netting,
      'store': instance.store,
      'first_approver': instance.firstApprover,
    };
