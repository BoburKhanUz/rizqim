// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tip_mode.dart';
import 'tip_pool_rule.dart';
import 'tip_pool_period.dart';
import 'roles.dart';

part 'v1_owner_pos_tips_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosTipsSettingsRequestBody {
  const V1OwnerPosTipsSettingsRequestBody({
    this.mode,
    this.poolRule,
    this.poolPeriod,
    this.roles,
    this.roleWeights,
    this.branchId,
  });

  factory V1OwnerPosTipsSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosTipsSettingsRequestBodyFromJson(json);

  final TipMode? mode;
  @JsonKey(name: 'pool_rule')
  final TipPoolRule? poolRule;
  @JsonKey(name: 'pool_period')
  final TipPoolPeriod? poolPeriod;
  final List<Roles>? roles;
  @JsonKey(name: 'role_weights')
  final List<num>? roleWeights;

  /// The branch (a branch-bound caller: their own when omitted).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosTipsSettingsRequestBodyToJson(this);
}
