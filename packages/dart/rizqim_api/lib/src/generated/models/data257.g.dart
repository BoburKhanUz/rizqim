// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data257.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data257 _$Data257FromJson(Map<String, dynamic> json) => Data257(
  qrs: (json['qrs'] as List<dynamic>)
      .map((e) => Qrs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data257ToJson(Data257 instance) => <String, dynamic>{
  'qrs': instance.qrs,
};
