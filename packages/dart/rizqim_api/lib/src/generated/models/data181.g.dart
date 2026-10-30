// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data181.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data181 _$Data181FromJson(Map<String, dynamic> json) => Data181(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
  summary: Summary4.fromJson(json['summary'] as Map<String, dynamic>),
);

Map<String, dynamic> _$Data181ToJson(Data181 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'entries': instance.entries,
  'summary': instance.summary,
};
