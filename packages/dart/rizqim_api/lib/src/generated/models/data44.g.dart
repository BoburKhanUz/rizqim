// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'data44.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Data44 _$Data44FromJson(Map<String, dynamic> json) => Data44(
  id: (json['id'] as num).toInt(),
  uuid: json['uuid'] as String,
  storeId: (json['store_id'] as num?)?.toInt(),
  name: json['name'] as String,
  email: json['email'] as String?,
  telegramId: json['telegram_id'] as String?,
  telegramUsername: json['telegram_username'] as String?,
  telegramPhotoUrl: json['telegram_photo_url'] as String?,
  phone: json['phone'] as String?,
  role: UserRole.fromJson(json['role'] as String),
  emailVerifiedAt: json['email_verified_at'] as String?,
  createdAt: json['created_at'] as String?,
  updatedAt: json['updated_at'] as String?,
  deletedAt: json['deleted_at'] as String?,
  address: json['address'] as String?,
  phoneVerifiedAt: json['phone_verified_at'] as String?,
  branchId: (json['branch_id'] as num?)?.toInt(),
  referredBy: (json['referred_by'] as num?)?.toInt(),
  balance: json['balance'] as String,
  pinSetAt: json['pin_set_at'] as String?,
  staffCode: json['staff_code'] as String?,
  courierType: CourierType.fromJson(json['courier_type'] as String),
  branch: json['branch'] == null
      ? null
      : Branch7.fromJson(json['branch'] as Map<String, dynamic>),
  telegramInviteToken: json['telegram_invite_token'] as String?,
);

Map<String, dynamic> _$Data44ToJson(Data44 instance) => <String, dynamic>{
  'id': instance.id,
  'uuid': instance.uuid,
  'store_id': instance.storeId,
  'name': instance.name,
  'email': instance.email,
  'telegram_id': instance.telegramId,
  'telegram_username': instance.telegramUsername,
  'telegram_photo_url': instance.telegramPhotoUrl,
  'phone': instance.phone,
  'role': instance.role,
  'email_verified_at': instance.emailVerifiedAt,
  'created_at': instance.createdAt,
  'updated_at': instance.updatedAt,
  'deleted_at': instance.deletedAt,
  'address': instance.address,
  'phone_verified_at': instance.phoneVerifiedAt,
  'branch_id': instance.branchId,
  'referred_by': instance.referredBy,
  'balance': instance.balance,
  'pin_set_at': instance.pinSetAt,
  'staff_code': instance.staffCode,
  'courier_type': instance.courierType,
  'branch': instance.branch,
  'telegram_invite_token': instance.telegramInviteToken,
};
