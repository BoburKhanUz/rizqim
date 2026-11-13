// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'meta3.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Meta3 _$Meta3FromJson(Map<String, dynamic> json) => Meta3(
  currentPage: (json['current_page'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
);

Map<String, dynamic> _$Meta3ToJson(Meta3 instance) => <String, dynamic>{
  'current_page': instance.currentPage,
  'last_page': instance.lastPage,
  'per_page': instance.perPage,
  'total': instance.total,
};
