// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'write_offs2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

WriteOffs2 _$WriteOffs2FromJson(Map<String, dynamic> json) => WriteOffs2(
  reason: json['reason'] as String,
  label: json['label'] as String,
  count: json['count'] as String,
  quantity: json['quantity'] as num,
);

Map<String, dynamic> _$WriteOffs2ToJson(WriteOffs2 instance) =>
    <String, dynamic>{
      'reason': instance.reason,
      'label': instance.label,
      'count': instance.count,
      'quantity': instance.quantity,
    };
