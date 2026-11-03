// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data87.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data87 _$Data87FromJson(Map<String, dynamic> json) => Data87(
  data: (json['data'] as List<dynamic>)
      .map((e) => Data86.fromJson(e as Map<String, dynamic>))
      .toList(),
  currentPage: (json['current_page'] as num).toInt(),
  lastPage: (json['last_page'] as num).toInt(),
  perPage: (json['per_page'] as num).toInt(),
  total: (json['total'] as num).toInt(),
  from: (json['from'] as num?)?.toInt(),
  to: (json['to'] as num?)?.toInt(),
);

Map<String, dynamic> _$Data87ToJson(Data87 instance) => <String, dynamic>{
  'data': instance.data,
  'current_page': instance.currentPage,
  'last_page': instance.lastPage,
  'per_page': instance.perPage,
  'total': instance.total,
  'from': instance.from,
  'to': instance.to,
};
