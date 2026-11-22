// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data162.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data162 _$Data162FromJson(Map<String, dynamic> json) => Data162(
  enforce: json['enforce'] as bool,
  signingTills: (json['signing_tills'] as num).toInt(),
  unsignedTills: (json['unsigned_tills'] as List<dynamic>)
      .map((e) => UnsignedTills2.fromJson(e as Map<String, dynamic>))
      .toList(),
);

Map<String, dynamic> _$Data162ToJson(Data162 instance) => <String, dynamic>{
  'enforce': instance.enforce,
  'signing_tills': instance.signingTills,
  'unsigned_tills': instance.unsignedTills,
};
