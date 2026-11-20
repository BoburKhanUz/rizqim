// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data254.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data254 _$Data254FromJson(Map<String, dynamic> json) => Data254(
  qrs: (json['qrs'] as List<dynamic>)
      .map((e) => Qrs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data254ToJson(Data254 instance) => <String, dynamic>{
  'qrs': instance.qrs,
};
