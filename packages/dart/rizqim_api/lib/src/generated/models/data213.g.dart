// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data213.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data213 _$Data213FromJson(Map<String, dynamic> json) => Data213(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data213ToJson(Data213 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
