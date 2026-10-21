// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers7.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers7 _$Modifiers7FromJson(Map<String, dynamic> json) => Modifiers7(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers7ToJson(Modifiers7 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
