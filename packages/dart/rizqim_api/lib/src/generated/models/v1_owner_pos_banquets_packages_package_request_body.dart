// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'items8.dart';

part 'v1_owner_pos_banquets_packages_package_request_body.g.dart';

@JsonSerializable()
class V1OwnerPosBanquetsPackagesPackageRequestBody {
  const V1OwnerPosBanquetsPackagesPackageRequestBody({
    this.name,
    this.perPersonPrice,
    this.minimumGuests,
    this.included,
    this.description,
    this.isActive,
    this.sortOrder,
    this.items,
  });

  factory V1OwnerPosBanquetsPackagesPackageRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerPosBanquetsPackagesPackageRequestBodyFromJson(json);

  final String? name;
  @JsonKey(name: 'per_person_price')
  final num? perPersonPrice;
  @JsonKey(name: 'minimum_guests')
  final int? minimumGuests;
  final List<String?>? included;
  final String? description;
  @JsonKey(name: 'is_active')
  final bool? isActive;
  @JsonKey(name: 'sort_order')
  final int? sortOrder;
  final List<Items8>? items;

  Map<String, Object?> toJson() =>
      _$V1OwnerPosBanquetsPackagesPackageRequestBodyToJson(this);
}
