// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data33.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data33 _$Data33FromJson(Map<String, dynamic> json) => Data33(
  branchId: (json['branch_id'] as num).toInt(),
  from: json['from'] as String,
  to: json['to'] as String,
  reasons: json['reasons'] as List<dynamic>,
  writeOffs: (json['write_offs'] as List<dynamic>)
      .map((e) => WriteOffs.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data33ToJson(Data33 instance) => <String, dynamic>{
  'branch_id': instance.branchId,
  'from': instance.from,
  'to': instance.to,
  'reasons': instance.reasons,
  'write_offs': instance.writeOffs,
};
