// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'modifiers33.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Modifiers33 _$Modifiers33FromJson(Map<String, dynamic> json) => Modifiers33(
  name: json['name'] as String,
  quantity: (json['quantity'] as num).toInt(),
  groupName: json['group_name'] as String?,
  priceDelta: json['price_delta'] as String?,
);

Map<String, dynamic> _$Modifiers33ToJson(Modifiers33 instance) =>
    <String, dynamic>{
      'name': instance.name,
      'group_name': instance.groupName,
      'quantity': instance.quantity,
      'price_delta': instance.priceDelta,
    };
