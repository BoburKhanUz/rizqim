// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data244.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data244 _$Data244FromJson(Map<String, dynamic> json) => Data244(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data244ToJson(Data244 instance) => <String, dynamic>{
  'staff': instance.staff,
};
