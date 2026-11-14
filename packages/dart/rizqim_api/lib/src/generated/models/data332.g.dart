// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data332.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data332 _$Data332FromJson(Map<String, dynamic> json) => Data332(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing2.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data332ToJson(Data332 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
