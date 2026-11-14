// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'osh3.dart';
import 'portion_weights3.dart';

part 'v1_owner_pos_banquets_ops_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsOpsSettingsRequestBody {
  const V1OwnerPosBanquetsOpsSettingsRequestBody({
    required this.osh,
    this.version,
    this.reservePercent,
    this.portionWeights,
    this.packageMxik,
    this.packagePackageCode,
    this.hallMxik,
    this.hallPackageCode,
    this.extraMxik,
    this.extraPackageCode,
    this.contractTemplate,
  });

  factory V1OwnerPosBanquetsOpsSettingsRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsOpsSettingsRequestBodyFromJson(json);

  final int? version;
  @JsonKey(name: 'reserve_percent')
  final num? reservePercent;
  final Osh3 osh;
  @JsonKey(name: 'portion_weights')
  final List<PortionWeights3>? portionWeights;
  @JsonKey(name: 'package_mxik')
  final String? packageMxik;
  @JsonKey(name: 'package_package_code')
  final String? packagePackageCode;
  @JsonKey(name: 'hall_mxik')
  final String? hallMxik;
  @JsonKey(name: 'hall_package_code')
  final String? hallPackageCode;
  @JsonKey(name: 'extra_mxik')
  final String? extraMxik;
  @JsonKey(name: 'extra_package_code')
  final String? extraPackageCode;
  @JsonKey(name: 'contract_template')
  final String? contractTemplate;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsOpsSettingsRequestBodyToJson(this);
}
