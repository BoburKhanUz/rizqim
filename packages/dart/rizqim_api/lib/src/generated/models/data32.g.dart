// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data32.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data32 _$Data32FromJson(Map<String, dynamic> json) => Data32(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  reasons: json['reasons'] as List<dynamic>,
  writeOffs: (json['write_offs'] as List<dynamic>)
      .map((e) => WriteOffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data32ToJson(Data32 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'reasons': instance.reasons,
  'write_offs': instance.writeOffs,
};
