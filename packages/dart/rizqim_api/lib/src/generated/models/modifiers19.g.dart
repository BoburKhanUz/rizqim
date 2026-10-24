// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers19.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers19 _$Modifiers19FromJson(Map<String, dynamic> json) => Modifiers19(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers19ToJson(Modifiers19 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
