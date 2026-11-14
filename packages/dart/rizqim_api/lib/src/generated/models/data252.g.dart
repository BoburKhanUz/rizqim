// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data252.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data252 _$Data252FromJson(Map<String, dynamic> json) => Data252(
  qrs: (json['qrs'] as List<dynamic>)
      .map((e) => Qrs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data252ToJson(Data252 instance) => <String, dynamic>{
  'qrs': instance.qrs,
};
