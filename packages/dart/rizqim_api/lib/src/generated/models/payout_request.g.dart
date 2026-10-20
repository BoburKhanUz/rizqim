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
      store: json['store'] == null
          ? null
          : Store.fromJson(json['store'] as Map<String, dynamic>),
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
      'store': instance.store,
    };
