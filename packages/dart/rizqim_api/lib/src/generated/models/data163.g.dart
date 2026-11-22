// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data163.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data163 _$Data163FromJson(Map<String, dynamic> json) => Data163(
  enforce: json['enforce'] as bool,
  signingTills: (json['signing_tills'] as num).toInt(),
  unsignedTills: (json['unsigned_tills'] as List<dynamic>)
      .map((e) => UnsignedTills2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data163ToJson(Data163 instance) => <String, dynamic>{
  'enforce': instance.enforce,
  'signing_tills': instance.signingTills,
  'unsigned_tills': instance.unsignedTills,
};
