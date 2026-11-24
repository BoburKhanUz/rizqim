// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data249.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data249 _$Data249FromJson(Map<String, dynamic> json) => Data249(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff11.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data249ToJson(Data249 instance) => <String, dynamic>{
  'staff': instance.staff,
};
