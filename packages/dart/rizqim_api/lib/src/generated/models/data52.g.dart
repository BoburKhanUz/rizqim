// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data52.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data52 _$Data52FromJson(Map<String, dynamic> json) => Data52(
  branchId: (json['branch_id'] as num).toInt(),
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data52ToJson(Data52 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'batches': instance.batches,
};
