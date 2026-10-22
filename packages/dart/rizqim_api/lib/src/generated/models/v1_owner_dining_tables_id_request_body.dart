// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'kind2.dart';
import 'min_spend_mode.dart';
import 'layout7.dart';

part 'v1_owner_dining_tables_id_request_body.g.dart';

@JsonSerializable()
class V1OwnerDiningTablesIdRequestBody {
  const V1OwnerDiningTablesIdRequestBody({
    this.hallId,
    this.name,
    this.kind,
    this.seats,
    this.sortOrder,
    this.isActive,
    this.minSpend,
    this.minSpendMode,
    this.layout,
  });

  factory V1OwnerDiningTablesIdRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerDiningTablesIdRequestBodyFromJson(json);

  @JsonKey(name: 'hall_id')
  final int? hallId;
  final String? name;
  final Kind2? kind;
  final int? seats;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'min_spend')
  final num? minSpend;
  @JsonKey(name: 'min_spend_mode')
  final MinSpendMode? minSpendMode;
  final Layout7? layout;

  Map<String, Object?> toJson() =>
      _$V1OwnerDiningTablesIdRequestBodyToJson(this);
}
