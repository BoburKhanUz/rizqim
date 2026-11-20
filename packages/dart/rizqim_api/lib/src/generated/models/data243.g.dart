// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data243.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data243 _$Data243FromJson(Map<String, dynamic> json) => Data243(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data243ToJson(Data243 instance) => <String, dynamic>{
  'staff': instance.staff,
};
