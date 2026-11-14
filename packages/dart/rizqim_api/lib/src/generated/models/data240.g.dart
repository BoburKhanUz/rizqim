// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data240.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data240 _$Data240FromJson(Map<String, dynamic> json) => Data240(
  staff: (json['staff'] as List<dynamic>)
      .map((e) => Staff10.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data240ToJson(Data240 instance) => <String, dynamic>{
  'staff': instance.staff,
};
