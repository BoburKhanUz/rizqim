// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data259.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data259 _$Data259FromJson(Map<String, dynamic> json) => Data259(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing2.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data259ToJson(Data259 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
