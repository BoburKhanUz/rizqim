// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers10.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers10 _$Modifiers10FromJson(Map<String, dynamic> json) => Modifiers10(
  id: (json['id'] as num).toInt(),
  name: json['name'] as String,
  groupName: json['group_name'] as String?,
  quantity: (json['quantity'] as num).toInt(),
  priceDelta: json['price_delta'] as String,
);

Map<String, dynamic> _$Modifiers10ToJson(Modifiers10 instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
