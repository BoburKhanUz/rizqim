// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_pos_service_charge_rules_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosServiceChargeRulesRequestBody {
  const V1OwnerPosServiceChargeRulesRequestBody({
    this.byOrderType,
    this.branchId,
    this.hallId,
    this.largeGroupMinGuests,
    this.largeGroupPercent,
    this.coverAmount,
    this.notice,
  });

  factory V1OwnerPosServiceChargeRulesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosServiceChargeRulesRequestBodyFromJson(json);

  /// Percent by order type: {hall, table_service, takeaway}.
  @JsonKey(name: 'by_order_type')
  final Map<String, String>? byOrderType;

  /// The branch (a branch-bound caller: their own when omitted).
  @JsonKey(name: 'branch_id')
  final int? branchId;

  /// One hall's own row.
  @JsonKey(name: 'hall_id')
  final int? hallId;

  /// Guests from which the large-group percent applies.
  @JsonKey(name: 'large_group_min_guests')
  final int? largeGroupMinGuests;

  /// The large-group percent.
  @JsonKey(name: 'large_group_percent')
  final String? largeGroupPercent;

  /// The cover per guest (so'm).
  @JsonKey(name: 'cover_amount')
  final String? coverAmount;

  /// The notice on the bill (null: worked out from the rules).
  final String? notice;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosServiceChargeRulesRequestBodyToJson(this);
}
