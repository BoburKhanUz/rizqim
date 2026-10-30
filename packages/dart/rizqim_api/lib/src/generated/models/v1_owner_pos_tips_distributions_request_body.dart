// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'participants.dart';

part 'v1_owner_pos_tips_distributions_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosTipsDistributionsRequestBody {
  const V1OwnerPosTipsDistributionsRequestBody({
    required this.businessDate,
    this.participants,
    this.branchId,
    this.shiftUuid,
  });

  factory V1OwnerPosTipsDistributionsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosTipsDistributionsRequestBodyFromJson(json);

  /// The people of the split: [{staff_id, minutes?}] (null: who worked).
  final List<Participants>? participants;

  /// The branch (a branch-bound caller: their own when omitted).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// The business day (YYYY-MM-DD).
  @JsonKey(name: 'business_date')
  final String businessDate;

  /// One shift instead of the whole day.
  @JsonKey(name: 'shift_uuid')
  final String? shiftUuid;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosTipsDistributionsRequestBodyToJson(this);
}
