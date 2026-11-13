// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data200.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data200 _$Data200FromJson(Map<String, dynamic> json) => Data200(
  qrs: (json['qrs'] as List<dynamic>)
      .map((e) => Qrs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data200ToJson(Data200 instance) => <String, dynamic>{
  'qrs': instance.qrs,
};
