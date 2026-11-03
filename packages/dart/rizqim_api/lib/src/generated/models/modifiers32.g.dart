// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers32.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers32 _$Modifiers32FromJson(Map<String, dynamic> json) => Modifiers32(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers32ToJson(Modifiers32 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
