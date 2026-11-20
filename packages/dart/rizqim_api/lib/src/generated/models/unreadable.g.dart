// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'unreadable.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Unreadable _$UnreadableFromJson(Map<String, dynamic> json) => Unreadable(
  index: (json['index'] as num).toInt(),
  opId: json['op_id'] as String?,
  seq: (json['seq'] as num?)?.toInt(),
  reason: json['reason'] as String,
  message: json['message'] as String,
);

Map<String, dynamic> _$UnreadableToJson(Unreadable instance) =>
    <String, dynamic>{
      'index': instance.index,
      'op_id': instance.opId,
      'seq': instance.seq,
      'reason': instance.reason,
      'message': instance.message,
    };
