// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data255.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data255 _$Data255FromJson(Map<String, dynamic> json) => Data255(
  qrs: (json['qrs'] as List<dynamic>)
      .map((e) => Qrs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data255ToJson(Data255 instance) => <String, dynamic>{
  'qrs': instance.qrs,
};
