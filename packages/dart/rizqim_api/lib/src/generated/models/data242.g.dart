// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data242.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data242 _$Data242FromJson(Map<String, dynamic> json) => Data242(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data242ToJson(Data242 instance) => <String, dynamic>{
  'staff': instance.staff,
};
