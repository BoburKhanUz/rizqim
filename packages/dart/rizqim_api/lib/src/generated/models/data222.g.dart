// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data222.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data222 _$Data222FromJson(Map<String, dynamic> json) => Data222(
  businessDate: json['business_date'] as String,
  preparing: (json['preparing'] as List<dynamic>)
      .map((e) => Preparing.fromJson(e as Map<String, dynamic>))
      .toList(),
  ready: (json['ready'] as List<dynamic>)
      .map((e) => Ready.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data222ToJson(Data222 instance) => <String, dynamic>{
  'business_date': instance.businessDate,
  'preparing': instance.preparing,
  'ready': instance.ready,
};
