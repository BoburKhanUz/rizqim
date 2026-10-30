// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers24.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers24 _$Modifiers24FromJson(Map<String, dynamic> json) => Modifiers24(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers24ToJson(Modifiers24 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
