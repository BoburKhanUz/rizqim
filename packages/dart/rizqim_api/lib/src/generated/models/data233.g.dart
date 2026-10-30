// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data233.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data233 _$Data233FromJson(Map<String, dynamic> json) => Data233(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing2.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data233ToJson(Data233 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
