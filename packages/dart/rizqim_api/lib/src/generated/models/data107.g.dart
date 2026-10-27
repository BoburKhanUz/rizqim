// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data107.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data107 _$Data107FromJson(Map<String, dynamic> json) => Data107(
  id: (json['id'] as num).toInt(),
  amount: json['amount'] as String,
  card: json['card'] as String,
  status: Status4.fromJson(json['status'] as String),
  notes: json['notes'] as String?,
  resolutionNotes: json['resolution_notes'] as String?,
  processedAt: json['processed_at'] as String?,
  createdAt: json['created_at'] as String,
);

Map<String, dynamic> _$Data107ToJson(Data107 instance) => <String, dynamic>{
  'id': instance.id,
  'amount': instance.amount,
  'card': instance.card,
  'status': instance.status,
  'notes': instance.notes,
  'resolution_notes': instance.resolutionNotes,
  'processed_at': instance.processedAt,
  'created_at': instance.createdAt,
};
