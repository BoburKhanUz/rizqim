// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data261.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data261 _$Data261FromJson(Map<String, dynamic> json) => Data261(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing2.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data261ToJson(Data261 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
