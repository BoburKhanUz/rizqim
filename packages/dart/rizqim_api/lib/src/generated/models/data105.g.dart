// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data105.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data105 _$Data105FromJson(Map<String, dynamic> json) => Data105(
  id: (json['id'] as num).toInt(),
  amount: json['amount'] as String,
  card: json['card'] as String,
  status: Status4.fromJson(json['status'] as String),
  notes: json['notes'] as String?,
  resolutionNotes: json['resolution_notes'] as String?,
  processedAt: json['processed_at'] as String?,
  createdAt: json['created_at'] as String,
  storeId: (json['store_id'] as num).toInt(),
  cardNumber: json['card_number'] as String,
  processedBy: (json['processed_by'] as num?)?.toInt(),
  updatedAt: json['updated_at'] as String?,
);

Map<String, dynamic> _$Data105ToJson(Data105 instance) => <String, dynamic>{
  'id': instance.id,
  'amount': instance.amount,
  'card': instance.card,
  'status': instance.status,
  'notes': instance.notes,
  'resolution_notes': instance.resolutionNotes,
  'processed_at': instance.processedAt,
  'created_at': instance.createdAt,
  'store_id': instance.storeId,
  'card_number': instance.cardNumber,
  'processed_by': instance.processedBy,
  'updated_at': instance.updatedAt,
};
