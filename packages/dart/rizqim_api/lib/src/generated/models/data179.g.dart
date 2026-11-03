// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data179.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data179 _$Data179FromJson(Map<String, dynamic> json) => Data179(
  branchId: (json['branch_id'] as num).toInt(),
  entries: (json['entries'] as List<dynamic>)
      .map((e) => Entries.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data179ToJson(Data179 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'entries': instance.entries,
};
