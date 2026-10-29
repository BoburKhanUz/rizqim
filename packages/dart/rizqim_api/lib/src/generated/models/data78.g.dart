// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data78.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data78 _$Data78FromJson(Map<String, dynamic> json) => Data78(
  data: (json['data'] as List<dynamic>)
      .map((e) => Data77.fromJson(e as Map<String, dynamic>))
      .toList(),
  currentPage: (json['current_page'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
  from: (json['from'] as num?)?.toInt(),
  to: (json['to'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data78ToJson(Data78 instance) => <String, dynamic>{
  'data': instance.data,
  'current_page': instance.currentPage,
  'last_page': instance.lastPage,
  'per_page': instance.perPage,
  'total': instance.total,
  'from': instance.from,
  'to': instance.to,
};
