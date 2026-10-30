// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_tips_distributions_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosTipsDistributionsRequestBody
_$V1OwnerPosTipsDistributionsRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosTipsDistributionsRequestBody(
      businessDate: json['business_date'] as String,
      participants: (json['participants'] as List<dynamic>?)
          ?.map((e) => Participants.fromJson(e as Map<String, dynamic>))
          .toList(),
      branchId: (json['branch_id'] as num?)?.toInt(),
      shiftUuid: json['shift_uuid'] as String?,
    );

Map<String, dynamic> _$V1OwnerPosTipsDistributionsRequestBodyToJson(
  V1OwnerPosTipsDistributionsRequestBody instance,
) => <String, dynamic>{
  'participants': instance.participants,
  'branch_id': instance.branchId,
  'business_date': instance.businessDate,
  'shift_uuid': instance.shiftUuid,
};
