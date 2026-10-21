// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data38.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data38 _$Data38FromJson(Map<String, dynamic> json) => Data38(
  currentPage: (json['current_page'] as num).toInt(),
  data: (json['data'] as List<dynamic>)
      .map((e) => Data37.fromJson(e as Map<String, dynamic>))
      .toList(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
);

Map<String, dynamic> _$Data38ToJson(Data38 instance) => <String, dynamic>{
  'current_page': instance.currentPage,
  'data': instance.data,
  'per_page': instance.perPage,
  'total': instance.total,
  'last_page': instance.lastPage,
};
