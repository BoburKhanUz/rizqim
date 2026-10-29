// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data33.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data33 _$Data33FromJson(Map<String, dynamic> json) => Data33(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  batches: (json['batches'] as List<dynamic>)
      .map((e) => Batches2.fromJson(e as Map<String, dynamic>))
      .toList(),
  days: (json['days'] as List<dynamic>)
      .map((e) => Days.fromJson(e as Map<String, dynamic>))
      .toList(),
  writeOffs: (json['write_offs'] as List<dynamic>)
      .map((e) => WriteOffs2.fromJson(e as Map<String, dynamic>))
      .toList(),
  inSets: json['in_sets'] as List<dynamic>,
);

Map<String, dynamic> _$Data33ToJson(Data33 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'batches': instance.batches,
  'days': instance.days,
  'write_offs': instance.writeOffs,
  'in_sets': instance.inSets,
};
