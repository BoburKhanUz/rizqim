// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers4 _$Modifiers4FromJson(Map<String, dynamic> json) => Modifiers4(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers4ToJson(Modifiers4 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
