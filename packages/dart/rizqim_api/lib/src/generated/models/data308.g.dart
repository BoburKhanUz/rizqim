// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data308.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data308 _$Data308FromJson(Map<String, dynamic> json) => Data308(
  order: Order11.fromJson(json['order'] as Map<String, dynamic>),
  bills: (json['bills'] as List<dynamic>)
      .map((e) => Bills3.fromJson(e as Map<String, dynamic>))
      .toList(),
  refunds: (json['refunds'] as List<dynamic>)
      .map((e) => Refunds3.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data308ToJson(Data308 instance) => <String, dynamic>{
  'order': instance.order,
  'bills': instance.bills,
  'refunds': instance.refunds,
};
